use std::fs::File;
use std::io::Read;

const DELAYS: [i32; 39] = [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 5, 5, 5, 7, 9, 12, 16, 22];
const WORDS: usize = 2243;

struct Pools {
    rates: [u32; 4],
    entries: [Vec<(u32, i32)>; 4],
    totals: [u32; 4],
}

impl Pools {
    fn parse(input: &[u32]) -> Result<Self, i32> {
        if input.len() < 5 || input[4] == 0 || input[4] > 1_000_000 || input.len() != 5 + input[4] as usize * 2 { return Err(1); }
        if input[..4].iter().map(|&v| v as u64).sum::<u64>() != 10000 { return Err(1); }
        let mut pools = Self { rates: [input[0], input[1], input[2], input[3]], entries: std::array::from_fn(|_| Vec::new()), totals: [0; 4] };
        for (index, entry) in input[5..].chunks_exact(2).enumerate() {
            let (tier, weight) = (entry[0] as usize, entry[1]);
            if tier >= 4 || weight == 0 || weight > 1_000_000 { return Err(1); }
            pools.totals[tier] = pools.totals[tier].checked_add(weight).filter(|&v| v <= 1_000_000_000).ok_or(1)?;
            pools.entries[tier].push((pools.totals[tier], index as i32));
        }
        for tier in 0..4 {
            if pools.rates[tier] > 0 && pools.totals[tier] == 0 { return Err(1); }
        }
        Ok(pools)
    }

    fn tier(&self, mut ticket: u32) -> Result<usize, i32> {
        if ticket == 0 || ticket > 10000 { return Err(1); }
        for tier in 0..4 {
            if ticket <= self.rates[tier] { return Ok(tier); }
            ticket -= self.rates[tier];
        }
        Err(1)
    }

    fn select(&self, tier: usize, ticket: u32) -> Result<i32, i32> {
        if ticket == 0 || ticket > self.totals[tier] { return Err(1); }
        let index = self.entries[tier].partition_point(|&(limit, _)| limit < ticket);
        Ok(self.entries[tier][index].1)
    }

    fn draw(&self, rng: &mut SecureRandom) -> Result<i32, i32> {
        let tier = self.tier(rng.below(10000)? + 1)?;
        self.select(tier, rng.below(self.totals[tier])? + 1)
    }
}

struct SecureRandom {
    file: File,
    bytes: [u8; 4096],
    cursor: usize,
}

impl SecureRandom {
    fn open() -> Result<Self, i32> {
        Ok(Self { file: File::open("/dev/urandom").map_err(|_| 2)?, bytes: [0; 4096], cursor: 4096 })
    }

    fn below(&mut self, bound: u32) -> Result<u32, i32> {
        if bound == 0 { return Err(1); }
        let threshold = bound.wrapping_neg() % bound;
        loop {
            if self.cursor == self.bytes.len() {
                self.file.read_exact(&mut self.bytes).map_err(|_| 2)?;
                self.cursor = 0;
            }
            let value = u32::from_ne_bytes(self.bytes[self.cursor..self.cursor + 4].try_into().map_err(|_| 2)?);
            self.cursor += 4;
            if value >= threshold { return Ok(value % bound); }
        }
    }
}

fn plan(pools: &Pools, rolls: u32, custom: bool, output: &mut [i32], rng: &mut SecureRandom) -> Result<(), i32> {
    let columns: &[usize] = match rolls { 1 => &[4], 3 => &[2, 4, 6], 5 => &[0, 2, 4, 6, 8], _ => return Err(1) };
    if output.len() != WORDS { return Err(1); }
    output.fill(-1);
    for i in 0..columns.len() { output[i] = pools.draw(rng)?; }
    let mut frame = [-1; 54];
    let mut previous = [-1; 54];
    for step in 0..39 {
        if !custom {
            for slot in 0..54 {
                let (column, row) = (slot % 9, slot / 9);
                if !columns.contains(&column) {
                    frame[slot] = if row == 3 && column + 1 >= columns[0] && column <= columns[columns.len() - 1] + 1 { -2 } else { -3 - rng.below(16)? as i32 };
                }
            }
        }
        for (i, &column) in columns.iter().enumerate() {
            for row in (1..6).rev() { frame[column + row * 9] = frame[column + (row - 1) * 9]; }
            frame[column] = if step == 35 { output[i] } else { pools.draw(rng)? };
        }
        let offset = 5 + step * 56;
        output[offset] = DELAYS[step];
        output[offset + 1] = if step >= 34 { [14, 16, 18, 20, 24][step - 34] } else { [5, 2, 9, 12][step % 4] };
        for slot in 0..54 { output[offset + 2 + slot] = if previous[slot] == frame[slot] { -19 } else { frame[slot] }; }
        previous = frame;
    }
    for &column in columns {
        for row in [0, 1, 2, 4, 5] { frame[column + row * 9] = -1; }
    }
    for slot in 0..54 { output[2189 + slot] = if previous[slot] == frame[slot] { -19 } else { frame[slot] }; }
    Ok(())
}

#[no_mangle]
pub extern "C" fn sw_roll_abi() -> u32 { 1 }

#[no_mangle]
pub unsafe extern "C" fn sw_roll_plan(input: *const u32, input_len: u32, rolls: u32, custom: u32, output: *mut i32, output_len: u32) -> i32 {
    if input.is_null() || output.is_null() || input_len < 5 || input_len > 2_000_005 || output_len as usize != WORDS || ![1, 3, 5].contains(&rolls) || custom > 1 { return 1; }
    let result = (|| {
        let pools = Pools::parse(std::slice::from_raw_parts(input, input_len as usize))?;
        plan(&pools, rolls, custom == 1, std::slice::from_raw_parts_mut(output, WORDS), &mut SecureRandom::open()?)
    })();
    result.err().unwrap_or(0)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn pools() -> Pools { Pools::parse(&[6000, 2500, 1200, 300, 5, 0, 1, 0, 3, 1, 1, 2, 1, 3, 1]).unwrap() }

    #[test]
    fn exact_tier_and_weight_boundaries() {
        let pools = pools();
        let mut counts = [0; 4];
        for ticket in 1..=10000 { counts[pools.tier(ticket).unwrap()] += 1; }
        assert_eq!(counts, [6000, 2500, 1200, 300]);
        assert_eq!((1..=4).map(|n| pools.select(0, n).unwrap()).collect::<Vec<_>>(), [0, 1, 1, 1]);
        assert!(pools.tier(0).is_err());
        assert!(pools.select(0, 5).is_err());
        let decimal = Pools::parse(&[6001, 2499, 1200, 300, 4, 0, 1, 1, 1, 2, 1, 3, 1]).unwrap();
        assert_eq!(decimal.tier(6001).unwrap(), 0);
        assert_eq!(decimal.tier(6002).unwrap(), 1);
    }

    #[test]
    fn invalid_abi_and_config() {
        assert!(Pools::parse(&[]).is_err());
        assert!(Pools::parse(&[9999, 0, 0, 0, 1, 0, 1]).is_err());
        assert!(Pools::parse(&[10000, 0, 0, 0, 1, 4, 1]).is_err());
        assert!(Pools::parse(&[10000, 0, 0, 0, 1, 0, 0]).is_err());
        assert!(Pools::parse(&[10000, 0, 0, 0, 1, 0, 1000001]).is_err());
        assert!(Pools::parse(&[10000, 0, 0, 0, 1, 1, 1]).is_err());
        let mut overflow = vec![10000, 0, 0, 0, 1001];
        for _ in 0..1001 { overflow.extend_from_slice(&[0, 1000000]); }
        assert!(Pools::parse(&overflow).is_err());
        assert_eq!(unsafe { sw_roll_plan(std::ptr::null(), 0, 1, 1, std::ptr::null_mut(), 0) }, 1);
    }

    #[test]
    fn random_bounds_and_entropy_failure() {
        let mut rng = SecureRandom::open().unwrap();
        for bound in [1, 3, 16, 10000, 1000000000] {
            for _ in 0..1000 { assert!(rng.below(bound).unwrap() < bound); }
        }
        rng.file = File::open("/dev/null").unwrap();
        rng.cursor = 4096;
        assert_eq!(rng.below(3), Err(2));
    }

    #[test]
    fn reels_timing_winners_and_fillers() {
        let pools = pools();
        for rolls in [1, 3, 5] {
            let columns: &[usize] = match rolls { 1 => &[4], 3 => &[2, 4, 6], _ => &[0, 2, 4, 6, 8] };
            for custom in [true, false] {
                let mut output = vec![77; WORDS + 2];
                plan(&pools, rolls, custom, &mut output[1..WORDS + 1], &mut SecureRandom::open().unwrap()).unwrap();
                assert_eq!(output[0], 77);
                assert_eq!(output[WORDS + 1], 77);
                let mut out = output[1..WORDS + 1].to_vec();
                let mut reconstructed = [-1; 54];
                for step in 0..40 {
                    let offset = if step == 39 { 2189 } else { 7 + step * 56 };
                    for slot in 0..54 {
                        let value = out[offset + slot];
                        if value == -19 { out[offset + slot] = reconstructed[slot]; }
                        else { assert_ne!(value, reconstructed[slot]); reconstructed[slot] = value; }
                    }
                }
                for (step, &delay) in DELAYS.iter().enumerate() {
                    let frame = &out[5 + step * 56..5 + (step + 1) * 56];
                    assert_eq!(frame[0], delay);
                    assert_eq!(frame[1], if step >= 34 { [14, 16, 18, 20, 24][step - 34] } else { [5, 2, 9, 12][step % 4] });
                    for slot in 0..54 {
                        let value = frame[2 + slot];
                        if columns.contains(&(slot % 9)) {
                            if slot / 9 <= step { assert!((0..5).contains(&value)); } else { assert_eq!(value, -1); }
                            if step > 0 && slot / 9 > 0 { assert_eq!(value, out[5 + (step - 1) * 56 + 2 + slot - 9]); }
                        } else if custom { assert_eq!(value, -1); }
                        else if slot / 9 == 3 && slot % 9 + 1 >= columns[0] && slot % 9 <= columns[columns.len() - 1] + 1 { assert_eq!(value, -2); }
                        else { assert!((-18..=-3).contains(&value)); }
                    }
                }
                for (i, &column) in columns.iter().enumerate() {
                    assert_eq!(out[5 + 35 * 56 + 2 + column], out[i]);
                    assert_eq!(out[5 + 38 * 56 + 2 + column + 27], out[i]);
                    assert_eq!(out[2189 + column + 27], out[i]);
                    for row in [0, 1, 2, 4, 5] { assert_eq!(out[2189 + column + row * 9], -1); }
                }
            }
        }
    }
}
